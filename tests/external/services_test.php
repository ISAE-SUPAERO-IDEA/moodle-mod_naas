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
 * Tests for the web service declarations of db/services.php.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\external;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\external\test_config::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\external\refresh_cache::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\external\get_nugget::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\external\view_nugget::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\external\get_nugget_preview::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\external\get_domain::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\external\get_structure::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\external\get_person::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\external\search_nuggets::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\external\check_catalogue::class)]
/**
 * Every function declared in db/services.php resolves to a described web service.
 *
 * The Vue widget calls the web services by name, so the names are checked too.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_naas\external\test_config
 * @covers \mod_naas\external\refresh_cache
 * @covers \mod_naas\external\get_nugget
 * @covers \mod_naas\external\view_nugget
 * @covers \mod_naas\external\get_nugget_preview
 * @covers \mod_naas\external\get_domain
 * @covers \mod_naas\external\get_structure
 * @covers \mod_naas\external\get_person
 * @covers \mod_naas\external\search_nuggets
 * @covers \mod_naas\external\check_catalogue
 */
final class services_test extends \advanced_testcase {
    #[\PHPUnit\Framework\Attributes\DataProvider('declared_service_provider')]
    /**
     * Every function declared in db/services.php must point to a method that exists
     * and describes its parameters and its return value.
     *
     * @dataProvider declared_service_provider
     * @param string $classname
     * @param string $methodname
     */
    public function test_declared_service_is_fully_described(string $classname, string $methodname): void {
        $this->assertTrue(method_exists($classname, $methodname), "{$classname}::{$methodname} is missing");
        $this->assertInstanceOf(
            \core_external\external_function_parameters::class,
            call_user_func([$classname, $methodname . '_parameters'])
        );
        $this->assertInstanceOf(
            \core_external\external_description::class,
            call_user_func([$classname, $methodname . '_returns'])
        );
    }

    /**
     * The functions declared in db/services.php.
     *
     * @return array
     */
    public static function declared_service_provider(): array {
        $cases = [];
        foreach (self::declared_functions() as $name => $definition) {
            $cases[$name] = ['\\' . ltrim($definition['classname'], '\\'), $definition['methodname']];
        }
        return $cases;
    }

    /**
     * The widget calls these names: each one runs the execute() method of its own class.
     */
    public function test_service_names_map_to_one_class_each(): void {
        $expected = [
            'mod_naas_test_config' => 'mod_naas\external\test_config',
            'mod_naas_refresh_cache' => 'mod_naas\external\refresh_cache',
            'mod_naas_get_nugget' => 'mod_naas\external\get_nugget',
            'mod_naas_view_nugget' => 'mod_naas\external\view_nugget',
            'mod_naas_get_nugget_preview' => 'mod_naas\external\get_nugget_preview',
            'mod_naas_get_domain' => 'mod_naas\external\get_domain',
            'mod_naas_get_structure' => 'mod_naas\external\get_structure',
            'mod_naas_get_person' => 'mod_naas\external\get_person',
            'mod_naas_search_nuggets' => 'mod_naas\external\search_nuggets',
            'mod_naas_check_catalogue' => 'mod_naas\external\check_catalogue',
        ];
        $functions = self::declared_functions();

        foreach ($expected as $name => $classname) {
            $this->assertSame($classname, $functions[$name]['classname'] ?? null, $name);
            $this->assertSame('execute', $functions[$name]['methodname'], $name);
            $this->assertTrue(is_subclass_of($classname, naas_external_base::class), $classname);
        }
        $this->assertSame('mod_naas\external\xapi', $functions['mod_naas_post_xapi_statement']['classname']);
        $this->assertCount(count($expected) + 1, $functions);
    }

    /**
     * The $functions array of db/services.php.
     *
     * @return array[]
     */
    private static function declared_functions(): array {
        global $CFG;
        $functions = [];
        require($CFG->dirroot . '/mod/naas/db/services.php');
        return $functions;
    }
}
