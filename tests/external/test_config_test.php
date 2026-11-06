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
 * Tests for the mod_naas_test_config web service.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\external;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/naas_client_stubs.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\external\test_config::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\external\naas_external_base::class)]
/**
 * Tests for the connection test of the plugin settings.
 *
 * NaaS is never reached: the NaaS client is injected through proxy_using().
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_naas\external\test_config
 * @covers \mod_naas\external\naas_external_base
 */
final class test_config_test extends \advanced_testcase {
    use naas_client_stubs;

    /** A NaaS search answer with one Nugget and one producer bucket. */
    private const SEARCH = '{"payload":{"items":[{"nugget_id":"n-1","name":"First","modification_date":"2026-05-01"}],'
        . '"aggregations":{"producers":{"buckets":[{"key":"managed_by:structure:isae","docCount":1}]}},"results_count":1}}';

    /**
     * Only an administrator may test the configuration.
     */
    public function test_test_config_requires_admin(): void {
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->expectException(\required_capability_exception::class);
        test_config::execute();
    }

    /**
     * test_config surfaces the transport failure of an unreachable NaaS endpoint.
     */
    public function test_test_config_executes(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $proxy = $this->proxy_using($this->failing_client());
        $this->expectException(\moodle_exception::class);
        $proxy::execute();
    }

    /**
     * Test connection returns the compacted NaaS answer and warms the catalogue snapshot.
     */
    public function test_test_config_returns_the_answer_and_warms_the_catalogue(): void {
        $this->resetAfterTest(true);
        set_config('naas_endpoint', 'https://api.naas.example/api', 'naas');
        \mod_naas\catalogue_cache::purge();
        $this->setAdminUser();
        $naas = $this->routing_client(function (string $service): string {
            if (strpos($service, '/nuggets/search') === 0) {
                return str_replace('{"items"', '{ "items"', self::SEARCH);
            }
            return '{"payload":{"items":[{"structure_id":"isae","acronym":"ISAE"}]}}';
        });

        $result = $this->proxy_using($naas)::execute();

        $this->assertSame(self::SEARCH, $result);
        $this->assertSame('/nuggets/search?is_default_version=1&page_size=2', $naas->calls[0]['service']);
        $snapshot = \mod_naas\catalogue_cache::get();
        $this->assertSame('ISAE', $snapshot['producers'][0]['acronym']);
        $this->assertSame(1, $snapshot['search']['results_count']);
    }

    /**
     * Name of a test_config subclass whose NaaS client is $client.
     *
     * @param \mod_naas\naas_client $client
     * @return string
     */
    private function proxy_using(\mod_naas\naas_client $client): string {
        $proxy = new class extends test_config {
            use injected_naas_client;
        };
        return $proxy::using($client);
    }
}
