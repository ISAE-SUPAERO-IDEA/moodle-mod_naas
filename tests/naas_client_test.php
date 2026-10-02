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
 * Unit tests for mod_naas\naas_client.
 *
 * The real HTTP layer (curl) is bypassed via a testable subclass that
 * overrides request_raw().  This lets us unit-test handle_result() logic,
 * URL construction, and all public API methods without a live NaaS endpoint.
 *
 * Tests that require actual network access belong to the external group and
 * are skipped in offline environments.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

defined('MOODLE_INTERNAL') || die();

use advanced_testcase;
use mod_naas\naas_client;
use stdClass;

require_once(__DIR__ . '/fixtures/testable_naas_client.php');

/**
 * Tests for mod_naas\naas_client.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/).
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 * @covers \mod_naas\naas_client
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class naas_client_test extends advanced_testcase {
    // Constructor / debug flag.

    /**
     * The constructor must store the config object.
     */
    public function test_constructor_stores_config(): void {
        $config         = new stdClass();
        $config->naas_endpoint = 'https://api.example.com';

        $client = new testable_naas_client($config);

        // Access via reflection because $config is protected.
        $ref = new \ReflectionProperty(naas_client::class, 'config');
        $ref->setAccessible(true);
        $this->assertSame($config, $ref->getValue($client));
    }

    /**
     * Debug flag defaults to false when naas_debug is absent from config.
     */
    public function test_debug_flag_defaults_false(): void {
        $client = $this->make_client();

        $ref = new \ReflectionProperty(naas_client::class, 'debug');
        $ref->setAccessible(true);
        $this->assertFalse($ref->getValue($client));
    }

    /**
     * Debug flag is set to true when naas_debug is truthy in config.
     */
    public function test_debug_flag_set_from_config(): void {
        $config             = $this->base_config();
        $config->naas_debug = true;

        $client = new testable_naas_client($config);

        $ref = new \ReflectionProperty(naas_client::class, 'debug');
        $ref->setAccessible(true);
        $this->assertTrue($ref->getValue($client));
    }

    // Handle_result (exercised via request()).

    /**
     * When the response has a payload property, request() returns the payload.
     */
    public function test_request_returns_payload_when_present(): void {
        $client            = $this->make_client();
        $client->stubjson = json_encode(['payload' => ['id' => 'nugget-xyz']]);

        $result = $client->request('GET', '/nuggets/nugget-xyz/default_version');

        $this->assertIsObject($result);
        $this->assertTrue(property_exists($result, 'id'));
        $this->assertSame('nugget-xyz', $result->id);
    }

    /**
     * When the payload is an object, request() returns the decoded stdClass.
     */
    public function test_request_returns_stdclass_payload(): void {
        $payload        = new stdClass();
        $payload->title = 'Aerodynamics 101';

        $client            = $this->make_client();
        $client->stubjson = json_encode(['payload' => $payload]);

        $result = $client->request('GET', '/test');

        $this->assertInstanceOf(stdClass::class, $result);
        $this->assertSame('Aerodynamics 101', $result->title);
    }

    /**
     * When the payload is null, request() falls through and returns the full
     * response object.
     */
    public function test_request_returns_full_response_when_payload_is_null(): void {
        $client            = $this->make_client();
        $client->stubjson = json_encode(['payload' => null]);

        // Payload is null → condition fails → handle_result returns $res.
        $result = $client->request('GET', '/test');

        $this->assertInstanceOf(stdClass::class, $result);
        $this->assertTrue(property_exists($result, 'payload'));
    }

    /**
     * When the response contains an error property (no payload), request()
     * returns the full response without throwing.
     */
    public function test_request_returns_response_on_error_property(): void {
        $client            = $this->make_client();
        $client->stubjson = json_encode(['error' => 'Something went wrong']);

        $result = $client->request('GET', '/test');

        $this->assertInstanceOf(stdClass::class, $result);
        $this->assertSame('Something went wrong', $result->error);
    }

    /**
     * When the JSON response is empty object, request() returns the decoded
     * object (neither payload nor error).
     */
    public function test_request_returns_empty_object_for_empty_json(): void {
        $client            = $this->make_client();
        $client->stubjson = '{}';

        $result = $client->request('GET', '/test');

        $this->assertInstanceOf(stdClass::class, $result);
    }

    // Request_raw routing (via captured args).

    /**
     * get_api_info() issues a GET request to the root endpoint.
     */
    public function test_get_api_info_sends_get_to_root(): void {
        $client            = $this->make_client();
        $client->stubjson = '{"payload": {"status": "ok"}}';

        $client->get_api_info();

        $this->assertSame('GET', $client->captured['protocol']);
        $this->assertSame('', $client->captured['service']);
    }

    /**
     * get_nugget_data() issues a GET request to the correct endpoint.
     */
    public function test_get_nugget_data_sends_get_to_correct_url(): void {
        $client            = $this->make_client();
        $client->stubjson = '{"payload": null}';

        $client->get_nugget_data('my-nugget-id');

        $this->assertSame('GET', $client->captured['protocol']);
        $this->assertSame('/nuggets/my-nugget-id/default_version', $client->captured['service']);
    }

    /**
     * get_nugget_lti_config() issues a GET with the structure_id param.
     */
    public function test_get_nugget_lti_config_sends_structure_id(): void {
        $config                    = $this->base_config();
        $config->naas_structure_id = 'my-structure';
        $client                    = new testable_naas_client($config);
        $client->stubjson         = '{"payload": null}';

        $client->get_nugget_lti_config('nugget-abc');

        $this->assertSame('GET', $client->captured['protocol']);
        $this->assertStringContainsString('/nuggets/nugget-abc/lti', $client->captured['service']);
        $this->assertSame('my-structure', $client->captured['params']['structure_id']);
    }

    /**
     * get_nugget_lti_config() uses an explicit structure_id when provided.
     */
    public function test_get_nugget_lti_config_uses_explicit_structure_id(): void {
        $client            = $this->make_client();
        $client->stubjson = '{"payload": null}';

        $client->get_nugget_lti_config('nugget-abc', 'override-structure');

        $this->assertSame('override-structure', $client->captured['params']['structure_id']);
    }

    /**
     * post_xapi_statement() issues a POST to the correct versioned endpoint.
     */
    public function test_post_xapi_statement_sends_post_to_correct_url(): void {
        $client            = $this->make_client();
        $client->stubjson = '{"payload": {"statusCode": 200, "statusMessage": "OK"}}';

        $data          = new stdClass();
        $data->user    = (object)['name' => 'Test', 'email' => 'test@example.com'];
        $data->body    = new stdClass();

        $client->post_xapi_statement('experienced', 'version-abc', $data);

        $this->assertSame('POST', $client->captured['protocol']);
        $this->assertSame('/versions/version-abc/records/experienced', $client->captured['service']);
    }

    /**
     * get_connected_user() issues a GET to /auth.
     */
    public function test_get_connected_user_calls_auth_endpoint(): void {
        $client            = $this->make_client();
        $client->stubjson = '{"payload": {"username": "admin"}}';

        $client->get_connected_user();

        $this->assertSame('GET', $client->captured['protocol']);
        $this->assertSame('/auth', $client->captured['service']);
    }

    // Error propagation.

    /**
     * When request_raw() throws (curl error), request() must propagate the
     * moodle_exception.
     */
    public function test_request_propagates_curl_exception(): void {
        $client             = $this->make_client();
        $client->stuberror = true;

        $this->expectException(\moodle_exception::class);
        $client->request('GET', '/test');
    }

    /**
     * A transport error thrown by get_nugget_data() is not swallowed.
     */
    public function test_get_nugget_data_propagates_exception(): void {
        $client             = $this->make_client();
        $client->stuberror = true;

        $this->expectException(\moodle_exception::class);
        $client->get_nugget_data('nugget-id');
    }

    // Tests for request_raw using injected curl factory.

    public function test_request_raw_executes_successfully(): void {
        $config = $this->base_config();

        $mockcurl = $this->createMock(\curl::class);
        $mockcurl->expects($this->once())->method('setopt');
        $mockcurl->expects($this->once())->method('get')->willReturn('{"status": "ok"}');
        $mockcurl->method('get_info')->willReturn(['http_code' => 200]);
        $mockcurl->method('get_errno')->willReturn(0);

        $client = new naas_client($config, function () use ($mockcurl) {
            return $mockcurl;
        });

        $response = $client->request_raw('GET', '/test');
        $this->assertEquals('{"status": "ok"}', $response);
    }

    /**
     * With naas_debug enabled, request_raw must log URL and config before calling curl.
     */
    public function test_request_raw_with_debug_logs_url_and_configuration(): void {
        $this->resetDebugging();

        $config = $this->base_config();
        $config->naas_debug = true;

        $mockcurl = $this->createMock(\curl::class);
        $mockcurl->expects($this->once())->method('setopt');
        $mockcurl->expects($this->once())->method('get')->willReturn('{}');
        $mockcurl->method('get_info')->willReturn(['http_code' => 200]);
        $mockcurl->method('get_errno')->willReturn(0);

        $client = new naas_client($config, function () use ($mockcurl) {
            return $mockcurl;
        });

        $client->request_raw('GET', '/instrumented');

        $debugging = $this->getDebuggingMessages();
        $this->resetDebugging();

        $this->assertCount(4, $debugging);
        $this->assertStringStartsWith('NAAS: Connecting to ', $debugging[0]->message);
        $this->assertStringContainsString('/instrumented', $debugging[0]->message);
        $this->assertStringStartsWith('NAAS: Configuration: ', $debugging[1]->message);
        $this->assertStringContainsString('naas_endpoint', $debugging[1]->message);
        $this->assertSame('NAAS: About to make request.', $debugging[2]->message);
        $this->assertSame('NAAS: Request completed.', $debugging[3]->message);
        foreach ($debugging as $entry) {
            $this->assertSame(DEBUG_DEVELOPER, $entry->level);
        }
    }

    public function test_request_raw_executes_post_successfully(): void {
        $config = $this->base_config();

        $mockcurl = $this->createMock(\curl::class);
        $mockcurl->expects($this->once())->method('setopt');
        $mockcurl->expects($this->once())->method('post')->with(
            $this->anything(),
            $this->equalTo('{"foo":"bar"}')
        )->willReturn('{"status": "posted"}');
        $mockcurl->method('get_info')->willReturn(['http_code' => 201]);
        $mockcurl->method('get_errno')->willReturn(0);

        $client = new naas_client($config, function () use ($mockcurl) {
            return $mockcurl;
        });

        $response = $client->request_raw('POST', '/test', ['foo' => 'bar']);
        $this->assertEquals('{"status": "posted"}', $response);
    }

    public function test_request_raw_handles_curl_error(): void {
        $config = $this->base_config();

        $mockcurl = $this->createMock(\curl::class);
        $mockcurl->method('get')->willReturn('');
        $mockcurl->method('get_info')->willReturn(['http_code' => 0]);
        $mockcurl->method('get_errno')->willReturn(28); // CURLE_OPERATION_TIMEDOUT.

        $client = new naas_client($config, function () use ($mockcurl) {
            return $mockcurl;
        });

        $this->expectException(\moodle_exception::class);
        $client->request_raw('GET', '/test');
    }

    public function test_request_raw_handles_http_error(): void {
        $config = $this->base_config();

        $mockcurl = $this->createMock(\curl::class);
        $mockcurl->method('get')->willReturn('Not Found');
        $mockcurl->method('get_info')->willReturn(['http_code' => 404]);
        $mockcurl->method('get_errno')->willReturn(0);

        $client = new naas_client($config, function () use ($mockcurl) {
            return $mockcurl;
        });

        $this->expectException(\moodle_exception::class);
        $client->request_raw('GET', '/test');
    }

    /**
     * Test SSL peer verification configuration.
     */
    public function test_request_raw_ssl_verification(): void {
        $config = $this->base_config();
        $config->naas_ssl_noverify = true;

        $mockcurl = $this->createMock(\curl::class);
        $mockcurl->expects($this->once())->method('setopt')->with($this->callback(function ($options) {
            return $options['CURLOPT_SSL_VERIFYPEER'] === false;
        }));
        $mockcurl->method('get')->willReturn('{}');
        $mockcurl->method('get_info')->willReturn(['http_code' => 200]);
        $mockcurl->method('get_errno')->willReturn(0);

        $client = new naas_client($config, function () use ($mockcurl) {
            return $mockcurl;
        });
        $client->request_raw('GET', '/test');
    }

    /**
     * Test timeout configuration.
     */
    public function test_request_raw_timeout(): void {
        $config = $this->base_config();
        $config->naas_timeout = 45;

        $mockcurl = $this->createMock(\curl::class);
        $mockcurl->expects($this->once())->method('setopt')->with($this->callback(function ($options) {
            return $options['CURLOPT_TIMEOUT'] === 45;
        }));
        $mockcurl->method('get')->willReturn('{}');
        $mockcurl->method('get_info')->willReturn(['http_code' => 200]);
        $mockcurl->method('get_errno')->willReturn(0);

        $client = new naas_client($config, function () use ($mockcurl) {
            return $mockcurl;
        });
        $client->request_raw('GET', '/test');
    }

    /**
     * Test impersonation and host headers.
     */
    public function test_request_raw_custom_headers(): void {
        $config = $this->base_config();
        $config->naas_impersonate = 'user123';
        $config->wwwroot = 'https://Moodle.example.com';

        $mockcurl = $this->createMock(\curl::class);
        $mockcurl->expects($this->once())->method('setopt')->with($this->callback(function ($options) {
            $headers = $options['CURLOPT_HTTPHEADER'];
            return in_array('X-NaaS-Impersonate:user123', $headers) &&
                   in_array('X-Host:https://Moodle.example.com', $headers);
        }));
        $mockcurl->method('get')->willReturn('{}');
        $mockcurl->method('get_info')->willReturn(['http_code' => 200]);
        $mockcurl->method('get_errno')->willReturn(0);

        $client = new naas_client($config, function () use ($mockcurl) {
            return $mockcurl;
        });
        $client->request_raw('GET', '/test');
    }

    /**
     * Test query parameter sanitization (stripping array indices).
     */
    public function test_request_raw_sanitizes_query_params(): void {
        $config = $this->base_config();

        $mockcurl = $this->createMock(\curl::class);
        // Http_build_query(['tags' => ['a', 'b']]) usually produces tags%5B0%5D=a&tags%5B1%5D=b.
        // Request_raw should change it to tags%5B%5D=a&tags%5B%5D=b.
        $mockcurl->expects($this->once())->method('get')->with($this->callback(function ($url) {
            // PHPUnit may XML-escape "&" as "&amp;" in failure output; normalise for assertions.
            $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            // Http_build_query gives tags%5B0%5D=a&…; preg_replace strips the numeric indices → tags=a&tags=b.
            return strpos($url, 'tags=a') !== false && strpos($url, 'tags=b') !== false;
        }))->willReturn('{}');
        $mockcurl->method('get_info')->willReturn(['http_code' => 200]);
        $mockcurl->method('get_errno')->willReturn(0);

        $client = new naas_client($config, function () use ($mockcurl) {
            return $mockcurl;
        });
        $client->request_raw('GET', '/test', null, ['tags' => ['a', 'b']]);
    }

    /**
     * Unsupported HTTP verbs must throw invalid_parameter_exception.
     */
    public function test_request_raw_rejects_unsupported_protocol(): void {
        $config = $this->base_config();
        $mockcurl = $this->createMock(\curl::class);
        $mockcurl->method('setopt');
        $client = new naas_client($config, function () use ($mockcurl) {
            return $mockcurl;
        });

        $this->expectException(\invalid_parameter_exception::class);
        $client->request_raw('DELETE', '/svc');
    }

    /**
     * HTTP 400 must map to error:naas_api:bad_request.
     */
    public function test_request_raw_http_400_maps_to_bad_request(): void {
        $config = $this->base_config();
        $mockcurl = $this->createMock(\curl::class);
        $mockcurl->method('setopt');
        $mockcurl->method('get')->willReturn('Bad');
        $mockcurl->method('get_info')->willReturn(['http_code' => 400]);
        $mockcurl->method('get_errno')->willReturn(0);
        $mockcurl->method('getResponse')->willReturn(['x-naas-api' => '1']);
        $client = new naas_client($config, function () use ($mockcurl) {
            return $mockcurl;
        });

        try {
            $client->request_raw('GET', '/svc');
            $this->fail('Expected moodle_exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:naas_api:bad_request', $e->errorcode);
        }
    }

    /**
     * HTTP 401 must map to error:naas_api:invalid_credentials.
     */
    public function test_request_raw_http_401_maps_to_invalid_credentials(): void {
        $config = $this->base_config();
        $mockcurl = $this->createMock(\curl::class);
        $mockcurl->method('setopt');
        $mockcurl->method('get')->willReturn('Unauthorized');
        $mockcurl->method('get_info')->willReturn(['http_code' => 401]);
        $mockcurl->method('get_errno')->willReturn(0);
        $mockcurl->method('getResponse')->willReturn(['x-naas-api' => '1']);
        $client = new naas_client($config, function () use ($mockcurl) {
            return $mockcurl;
        });

        try {
            $client->request_raw('GET', '/svc');
            $this->fail('Expected moodle_exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:naas_api:invalid_credentials', $e->errorcode);
        }
    }

    /**
     * HTTP 403 must map to error:naas_api:invalid_credentials.
     */
    public function test_request_raw_http_403_maps_to_invalid_credentials(): void {
        $config = $this->base_config();
        $mockcurl = $this->createMock(\curl::class);
        $mockcurl->method('setopt');
        $mockcurl->method('get')->willReturn('Forbidden');
        $mockcurl->method('get_info')->willReturn(['http_code' => 403]);
        $mockcurl->method('get_errno')->willReturn(0);
        $mockcurl->method('getResponse')->willReturn(['x-naas-api' => '1']);
        $client = new naas_client($config, function () use ($mockcurl) {
            return $mockcurl;
        });

        try {
            $client->request_raw('GET', '/svc');
            $this->fail('Expected moodle_exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:naas_api:invalid_credentials', $e->errorcode);
        }
    }

    /**
     * HTTP codes outside the explicit switch must map to error:naas_api:unknown.
     */
    public function test_request_raw_http_502_maps_to_unknown(): void {
        $config = $this->base_config();
        $mockcurl = $this->createMock(\curl::class);
        $mockcurl->method('setopt');
        $mockcurl->method('get')->willReturn('Bad Gateway');
        $mockcurl->method('get_info')->willReturn(['http_code' => 502]);
        $mockcurl->method('get_errno')->willReturn(0);
        $mockcurl->method('getResponse')->willReturn(['x-naas-api' => '1']);
        $client = new naas_client($config, function () use ($mockcurl) {
            return $mockcurl;
        });

        try {
            $client->request_raw('GET', '/svc');
            $this->fail('Expected moodle_exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:naas_api:unknown', $e->errorcode);
        }
    }

    /**
     * With naas_debug enabled, a curl transport error must log at DEBUG_DEVELOPER before throwing.
     */
    public function test_request_raw_curl_error_with_debug_logs_naas_error(): void {
        $this->resetDebugging();

        $config = $this->base_config();
        $config->naas_debug = true;

        $stubtransport = new class {
            /** @var string */
            public $error = 'connection reset';

            /**
             * Ignore curl option assignment.
             *
             * @param array $options
             * @SuppressWarnings(PHPMD.UnusedFormalParameter)
             */
            public function setopt($options): void {
            }

            /**
             * Return an empty transport body.
             *
             * @param string $url
             * @SuppressWarnings(PHPMD.UnusedFormalParameter)
             */
            public function get($url) {
                return '';
            }

            /**
             * Return the stubbed curl info.
             */
            public function get_info() {
                return ['http_code' => 0];
            }

            /**
             * Return the stubbed curl error number.
             */
            public function get_errno() {
                return 56;
            }
        };

        $client = new naas_client($config, static function () use ($stubtransport) {
            return $stubtransport;
        });

        try {
            $client->request_raw('GET', '/svc');
            $this->fail('Expected moodle_exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:proxy_naas_api:curl', $e->errorcode);
        }

        $debugging = $this->getDebuggingMessages();
        $this->resetDebugging();
        $this->assertNotEmpty($debugging);
        $last = end($debugging);
        $this->assertStringStartsWith('NAAS ERROR: Curl error: connection reset (Code: 56)', $last->message);
        $this->assertSame(DEBUG_DEVELOPER, $last->level);
    }

    /**
     * Invalid JSON response yields null decoded value and handle_result null branch.
     */
    public function test_request_invalid_json_body_returns_null(): void {
        $client = $this->make_client();
        $client->stubjson = 'not-valid-json-{';

        $this->assertNull($client->request('GET', '/svc'));
    }

    /**
     * With debug enabled, null JSON still flows through handle_result else-branch.
     */
    public function test_request_invalid_json_with_debug(): void {
        $this->resetDebugging();

        $config = $this->base_config();
        $config->naas_debug = true;
        $client = new testable_naas_client($config);
        $client->stubjson = 'not-valid-json-{';

        $this->assertNull($client->request('GET', '/svc'));

        $this->assertDebuggingCalled(
            get_string('error:naas_server_unexpected', 'naas'),
            DEBUG_NORMAL
        );
    }

    /**
     * Error payload with debug enabled exercises handle_result error branch logging.
     */
    public function test_request_error_payload_with_debug(): void {
        $config = $this->base_config();
        $config->naas_debug = true;
        $client = new testable_naas_client($config);
        $client->stubjson = json_encode(['error' => 'server-side']);

        $result = $client->request('GET', '/svc');
        $this->assertIsObject($result);
        $this->assertSame('server-side', $result->error);

        // Handle_result() logs twice in the error branch (NORMAL + DEVELOPER).
        $this->assertDebuggingCalledCount(2, [
            get_string('error:naas_server', 'naas'),
            json_encode('server-side', JSON_PRETTY_PRINT),
        ], [DEBUG_NORMAL, DEBUG_DEVELOPER]);
    }

    /**
     * Successful payload with naas_debug must log the payload at DEBUG_DEVELOPER.
     */
    public function test_request_payload_with_debug_logs_pretty_payload(): void {
        $this->resetDebugging();

        $config = $this->base_config();
        $config->naas_debug = true;
        $client = new testable_naas_client($config);
        $client->stubjson = json_encode(['payload' => ['k' => 'v']]);

        $result = $client->request('GET', '/svc');
        $this->assertIsObject($result);
        $this->assertSame('v', $result->k);

        $res = json_decode($client->stubjson);
        $expected = 'Payload: ' . json_encode($res->payload, JSON_PRETTY_PRINT);
        $this->assertDebuggingCalled($expected, DEBUG_DEVELOPER);
    }

    /**
     * Response without usable payload and without error key must log naas_server_unexpected at DEBUG_NORMAL.
     */
    public function test_request_unexpected_shape_with_debug_logs_unexpected(): void {
        $this->resetDebugging();

        $config = $this->base_config();
        $config->naas_debug = true;
        $client = new testable_naas_client($config);
        $client->stubjson = json_encode(['status' => 'orphan']);

        $result = $client->request('GET', '/svc');
        $this->assertIsObject($result);
        $this->assertSame('orphan', $result->status);

        $this->assertDebuggingCalled(
            get_string('error:naas_server_unexpected', 'naas'),
            DEBUG_NORMAL
        );
    }

    // Helpers.

    /**
     * Build a testable client with minimal config.
     *
     * @return testable_naas_client
     */
    private function make_client(): testable_naas_client {
        return new testable_naas_client($this->base_config());
    }

    /**
     * Minimal config object needed to construct naas_client.
     *
     * @return stdClass
     */
    private function base_config(): stdClass {
        $config                   = new stdClass();
        $config->naas_endpoint    = 'https://Naas.example.com/api';
        $config->naas_username    = 'testuser';
        $config->naas_password    = 'testpass';
        $config->naas_structure_id = 'default-structure';
        return $config;
    }
}
