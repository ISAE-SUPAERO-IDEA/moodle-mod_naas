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
 * Tests for the transport of the NaaS client: factory, default curl, query string and error mapping.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\naas_client::class)]
/**
 * Tests for naas_client::create(), the default curl client, the query string and the NaaS error codes.
 *
 * Nothing leaves the test site: curl::mock_response() answers the default client, a PHPUnit mock the others.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_naas\naas_client
 */
final class naas_client_transport_test extends \advanced_testcase {
    /**
     * Minimal client configuration.
     *
     * @return object
     */
    private function config(): object {
        return (object) [
            'naas_endpoint' => 'https://api.naas.example/api',
            'naas_username' => 'u',
            'naas_password' => 'p',
            'naas_structure_id' => 's',
        ];
    }

    /**
     * A curl mock answering with a status code, a body and the NaaS API header.
     *
     * @param int $code
     * @param string $body
     * @return \curl
     */
    private function naas_answering(int $code, string $body): \curl {
        $curl = $this->createMock(\curl::class);
        $curl->method('get')->willReturn($body);
        $curl->method('get_info')->willReturn(['http_code' => $code]);
        $curl->method('get_errno')->willReturn(0);
        $curl->method('getResponse')->willReturn(['X-NaaS-API' => '1']);
        return $curl;
    }

    /**
     * Outside Behat, create() builds the real client.
     */
    public function test_create_returns_the_http_client(): void {
        $client = naas_client::create($this->config());

        $this->assertSame(naas_client::class, get_class($client));
    }

    /**
     * Without a factory, the request goes through Moodle's curl client.
     */
    public function test_request_raw_uses_moodle_curl_by_default(): void {
        \curl::mock_response('{"payload":{"name":"NaaS"}}');
        $client = new naas_client($this->config());

        $this->assertSame('{"payload":{"name":"NaaS"}}', $client->request_raw('GET', '/'));
    }

    /**
     * Several query parameters are joined with '&', whatever Moodle's arg_separator.output is.
     */
    public function test_request_raw_joins_query_parameters_with_an_ampersand(): void {
        $this->assertSame('&amp;', ini_get('arg_separator.output'), 'Moodle escapes the separator by default.');
        $curl = $this->createMock(\curl::class);
        $curl->expects($this->once())->method('get')
            ->with('https://api.naas.example/api/structures?is_producer=true&page_size=100&page=1')
            ->willReturn('{"payload":[]}');
        $curl->method('get_info')->willReturn(['http_code' => 200]);
        $curl->method('get_errno')->willReturn(0);
        $client = new naas_client($this->config(), function () use ($curl) {
            return $curl;
        });

        $this->assertSame(
            '{"payload":[]}',
            $client->request_raw('GET', '/structures', null, ['is_producer' => 'true', 'page_size' => 100, 'page' => 1])
        );
    }

    /**
     * NaaS HTTP errors map to the plugin error codes.
     *
     * @dataProvider naas_error_provider
     * @param int $code
     * @param string $body
     * @param string $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('naas_error_provider')]
    public function test_request_raw_maps_naas_errors(int $code, string $body, string $expected): void {
        $curl = $this->naas_answering($code, $body);
        $client = new naas_client($this->config(), function () use ($curl) {
            return $curl;
        });

        try {
            $client->request_raw('GET', '/structures/x');
            $this->fail('Expected a NaaS error');
        } catch (\moodle_exception $e) {
            $this->assertSame($expected, $e->errorcode);
            $this->assertSame(['code' => $code, 'error' => $body, 'is_naas_api' => true], json_decode($e->debuginfo, true));
        }
    }

    /**
     * NaaS answers and the error code each one gives.
     *
     * @return array
     */
    public static function naas_error_provider(): array {
        return [
            'unknown institute' => [400, '{"error_code":"error_structure_not_found"}', 'error:naas_api:invalid_structure'],
            'other bad request' => [400, '{"error_code":"error_other"}', 'error:naas_api:bad_request'],
            'not found' => [404, '{"error":"no such structure"}', 'error:naas_api:not_found'],
        ];
    }
}
