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
 * Coverage for mod_naas/lib.php top-level constant registration.
 *
 * lib_test.php requires lib.php at file scope, so define() lines run before PCOV
 * attributes code to a test. This suite loads lib.php only inside an isolated process test.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

use advanced_testcase;

/**
 * Covers the module constants defined in lib.php.
 *
 * @covers ::naas_define_module_constants
 */
final class lib_constants_coverage_test extends advanced_testcase {
    /**
     * First require of lib.php in this PHP process must execute naas_define_module_constants().
     *
     * @runInSeparateProcess
     */
    public function test_lib_php_constant_defines_run_under_coverage(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/naas/lib.php');

        $this->assertTrue(defined('NAAS_MAX_ATTEMPT_OPTION'));
        $this->assertSame(10, (int) NAAS_MAX_ATTEMPT_OPTION);
        $this->assertSame(50, (int) NAAS_MAX_QPP_OPTION);
        $this->assertSame(5, (int) NAAS_MAX_DECIMAL_OPTION);
        $this->assertSame(7, (int) NAAS_MAX_Q_DECIMAL_OPTION);
        $this->assertSame('1', NAAS_GRADEHIGHEST);
        $this->assertSame('3', NAAS_ATTEMPTFIRST);
        $this->assertSame('4', NAAS_ATTEMPTLAST);

        naas_define_module_constants();
        $this->assertSame(10, (int) NAAS_MAX_ATTEMPT_OPTION);
    }
}
