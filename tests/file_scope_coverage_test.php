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
 * First load of the mod_naas files that run code at file scope.
 *
 * File-scope code (require_once, the outcome.php web entrypoint guard) runs once
 * per PHP process, usually while another test file is being loaded, before any
 * test starts. These tests load the files again in a fresh process. lib.php is
 * not among them: the Moodle PHPUnit bootstrap includes every mod/{name}/lib.php
 * (and gradelib.php) before coverage starts, in isolated processes too.
 *
 * They declare no coverage target on purpose. A "covers" target keeps only the
 * lines of that class or function, "coversNothing" keeps none, and file-scope
 * lines belong to no class or function: only a test without coverage metadata
 * records them. The process must also start without the parent's included files
 * (preserveGlobalState disabled), or they would be loaded before coverage starts.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

// No coverage target on purpose (see above), and moodle-cs does not know the
// preserveGlobalState tag that PHPUnit 9 reads.
// phpcs:disable moodle.PHPUnit.TestCaseCovers.Missing
// phpcs:disable moodle.Commenting.ValidTags.Invalid

/**
 * File-scope code of outcome.php, mod_form.php and the classes that require libraries.
 *
 * Nothing of the plugin may be required at the top of this file: the separate
 * process loads this file before it starts recording coverage.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class file_scope_coverage_test extends \advanced_testcase {
    /**
     * outcome.php loads its libraries but, under PHPUnit, does not process a request.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function test_outcome_php_first_load_only_declares_the_handler(): void {
        global $CFG, $DB;
        $this->resetAfterTest(true);
        if (class_exists('mod_naas_outcome', false)) {
            $this->markTestSkipped('outcome.php is already loaded.');
        }
        $before = $DB->count_records('naas_activity_outcome');

        require_once($CFG->dirroot . '/mod/naas/outcome.php');

        $this->assertTrue(class_exists('mod_naas_outcome', false));
        $this->assertTrue(function_exists('grade_get_grades'));
        $this->assertTrue(class_exists('completion_info', false));
        $this->assertTrue(defined('NAAS_ATTEMPTLAST'));
        $this->assertSame($before, $DB->count_records('naas_activity_outcome'));
    }

    /**
     * mod_form.php loads the course module form base class and the plugin library.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function test_mod_form_php_first_load_requires_its_libraries(): void {
        global $CFG;
        if (class_exists('mod_naas_mod_form', false)) {
            $this->markTestSkipped('mod_form.php is already loaded.');
        }

        require_once($CFG->dirroot . '/mod/naas/mod_form.php');

        $this->assertTrue(class_exists('moodleform_mod', false));
        $this->assertTrue(is_subclass_of('mod_naas_mod_form', 'moodleform_mod'));
        $this->assertTrue(defined('NAAS_GRADEHIGHEST'));
    }

    /**
     * Autoloading the classes that require libraries at file scope pulls those libraries in.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function test_classes_with_file_scope_requires_load_their_libraries(): void {
        if (
            class_exists(\mod_naas\admin\action_button_setting::class, false)
            || class_exists(\mod_naas\lti_outcome_oauth::class, false)
        ) {
            $this->markTestSkipped('The classes are already loaded.');
        }

        $this->assertTrue(class_exists(\mod_naas\admin\action_button_setting::class));
        $this->assertTrue(is_subclass_of(\mod_naas\admin\action_button_setting::class, \admin_setting::class));

        $this->assertTrue(class_exists(\mod_naas\lti_outcome_oauth::class));
        $this->assertTrue(class_exists(\moodle\mod\lti\OAuthServer::class, false));
        $this->assertTrue(class_exists(\moodle\mod\lti\TrivialOAuthDataStore::class, false));
    }
}
