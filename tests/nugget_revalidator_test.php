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
 * Tests for the nightly revalidation of the cached Nugget documents.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\nugget_revalidator::class)]
/**
 * Batch revalidation through `/nuggets?nugget_ids=…`, and the fallback to single calls.
 *
 * @covers \mod_naas\nugget_revalidator
 */
final class nugget_revalidator_test extends \advanced_testcase {
    /** @var object Plugin configuration used for the cache keys. */
    private $config;

    /**
     * Configure a NaaS connection.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('naas_endpoint', 'https://naas.example.test/api', 'naas');
        set_config('naas_username', 'moodle', 'naas');
        $this->config = (object) array_merge((array) get_config('naas'), (array) $GLOBALS['CFG']);
    }

    /**
     * A client that answers each request from a callback and records the URLs asked for.
     *
     * @param callable $answer function (string $service, ?array $params): string
     * @return naas_client
     */
    private function client(callable $answer): naas_client {
        return new class ($this->config, $answer) extends naas_client {
            /** @var callable */
            private $answer;
            /** @var array Requests made: [service, params]. */
            public $requests = [];

            /**
             * Keep the answering callback.
             *
             * @param object $config
             * @param callable $answer
             */
            public function __construct($config, callable $answer) {
                parent::__construct($config);
                $this->answer = $answer;
            }

            /**
             * Answer without any HTTP call.
             *
             * @param string $protocol
             * @param string $service
             * @param mixed $data
             * @param array|null $params
             * @return string
             */
            public function request_raw($protocol, $service, $data = null, $params = null): string {
                $this->requests[] = [$service, $params];
                return ($this->answer)($service, $params);
            }
        };
    }

    /**
     * A NaaS default-version document.
     *
     * @param string $id
     * @param string $version
     * @return array
     */
    private static function document(string $id, string $version = 'v1'): array {
        return [
            'nugget_id' => $id,
            'version_id' => $id . '-' . $version,
            'name' => 'Nugget ' . $id,
            'modification_date' => '2026-11-06T10:00:00Z',
            'is_default_version' => true,
        ];
    }

    /**
     * A `/nuggets` listing body.
     *
     * @param array $items
     * @return string
     */
    private static function listing(array $items): string {
        return json_encode(['payload' => [
            'items' => $items,
            'aggregations' => [],
            'page' => 0,
            'pages' => 1,
            'results_count' => count($items),
        ]]);
    }

    /**
     * The ids come from the site's activities, once each, malformed ones left out.
     */
    public function test_site_nugget_ids_lists_each_valid_id_once(): void {
        $course = $this->getDataGenerator()->create_course();
        foreach (['n-b', 'n-a', 'n-b', 'bad id/../x'] as $id) {
            $this->getDataGenerator()->create_module('naas', ['course' => $course->id, 'nugget_id' => $id]);
        }

        $this->assertSame(['n-a', 'n-b'], nugget_revalidator::site_nugget_ids());
    }

    /**
     * One request per batch of ids; every returned document lands in the cache.
     */
    public function test_batch_stores_every_returned_document(): void {
        $client = $this->client(function (string $service, ?array $params): string {
            $this->assertSame('/nuggets', $service);
            $this->assertSame('true', $params['is_default_version']);
            $this->assertSame(count($params['nugget_ids']), $params['page_size']);
            return self::listing(array_map([self::class, 'document'], $params['nugget_ids']));
        });

        $result = nugget_revalidator::run($client, $this->config, ['n-1', 'n-2']);

        $this->assertSame(['mode' => 'batch', 'stored' => 2, 'missing' => 0, 'failed' => 0], $result);
        $this->assertCount(1, $client->requests);
        $this->assertSame('n-2-v1', nugget_cache::get('n-2', $this->config)['version_id']);
    }

    /**
     * Ids are split into batches of BATCH_SIZE.
     */
    public function test_batches_are_capped(): void {
        $ids = [];
        for ($i = 0; $i < nugget_revalidator::BATCH_SIZE + 3; $i++) {
            $ids[] = 'n-' . $i;
        }
        $client = $this->client(function (string $service, ?array $params): string {
            return self::listing(array_map([self::class, 'document'], $params['nugget_ids']));
        });

        $result = nugget_revalidator::run($client, $this->config, $ids);

        $this->assertSame(count($ids), $result['stored']);
        $this->assertCount(2, $client->requests);
        $this->assertCount(3, $client->requests[1][1]['nugget_ids']);
    }

    /**
     * A Nugget NaaS no longer returns is dropped from the cache, so the form asks NaaS again.
     */
    public function test_missing_nugget_is_purged(): void {
        nugget_cache::store('n-gone', json_encode(self::document('n-gone')), $this->config);
        $client = $this->client(function (): string {
            return self::listing([self::document('n-kept', 'v2')]);
        });

        $result = nugget_revalidator::run($client, $this->config, ['n-gone', 'n-kept']);

        $this->assertSame(1, $result['stored']);
        $this->assertSame(1, $result['missing']);
        $this->assertNull(nugget_cache::get('n-gone', $this->config));
        $this->assertSame('n-kept-v2', nugget_cache::get('n-kept', $this->config)['version_id']);
    }

    /**
     * A NaaS that ignores nugget_ids (unrequested items) switches to single calls,
     * only for documents already cached, and the switch is remembered.
     */
    public function test_ignored_filter_falls_back_to_single_calls(): void {
        nugget_cache::store('n-open', json_encode(self::document('n-open')), $this->config);
        $client = $this->client(function (string $service): string {
            if ($service === '/nuggets') {
                return self::listing([self::document('n-other'), self::document('n-open')]);
            }
            $this->assertSame('/nuggets/n-open/default_version', $service);
            return json_encode(['payload' => self::document('n-open', 'v3')]);
        });

        $result = nugget_revalidator::run($client, $this->config, ['n-open', 'n-never-opened']);

        $this->assertSame(['mode' => 'single', 'stored' => 1, 'missing' => 0, 'failed' => 0], $result);
        $this->assertSame('n-open-v3', nugget_cache::get('n-open', $this->config)['version_id']);
        $this->assertTrue(nugget_revalidator::batch_unsupported());

        // The next run goes straight to single calls.
        $client->requests = [];
        nugget_revalidator::run($client, $this->config, ['n-open']);
        $this->assertSame([['/nuggets/n-open/default_version', null]], $client->requests);
    }

    /**
     * More results than ids also means the filter was ignored.
     */
    public function test_results_count_above_the_batch_means_unsupported(): void {
        $client = $this->client(function (string $service): string {
            $body = json_decode(self::listing([self::document('n-1')]), true);
            $body['payload']['results_count'] = 500;
            return json_encode($body);
        });

        $result = nugget_revalidator::run($client, $this->config, ['n-1']);

        $this->assertSame('single', $result['mode']);
        $this->assertTrue(nugget_revalidator::batch_unsupported());
    }

    /**
     * An unusable body is treated like an ignored filter.
     */
    public function test_unusable_body_is_unsupported(): void {
        $client = $this->client(function (): string {
            return '{"payload": "nope"}';
        });

        $this->assertSame('single', nugget_revalidator::run($client, $this->config, ['n-1'])['mode']);
    }

    /**
     * A failing batch keeps the cached copies and counts the failure.
     */
    public function test_failed_batch_keeps_cached_documents(): void {
        nugget_cache::store('n-1', json_encode(self::document('n-1')), $this->config);
        $client = $this->client(function (): string {
            throw new \moodle_exception('error:naas_api:not_found', 'naas');
        });

        $result = nugget_revalidator::run($client, $this->config, ['n-1']);

        $this->assertSame(['mode' => 'batch', 'stored' => 0, 'missing' => 0, 'failed' => 1], $result);
        $this->assertNotNull(nugget_cache::get('n-1', $this->config));
        $this->assertFalse(nugget_revalidator::batch_unsupported());
    }

    /**
     * Single calls: an undecodable answer purges the copy, an error keeps it.
     */
    public function test_single_calls_purge_or_keep(): void {
        nugget_cache::store('n-bad', json_encode(self::document('n-bad')), $this->config);
        nugget_cache::store('n-down', json_encode(self::document('n-down')), $this->config);
        \cache::make('mod_naas', nugget_cache::CACHE_AREA)->set(nugget_revalidator::UNSUPPORTED_KEY, (string) (time() + 60));
        $client = $this->client(function (string $service): string {
            if ($service === '/nuggets/n-down/default_version') {
                throw new \moodle_exception('error:naas_api:not_found', 'naas');
            }
            return '{"payload": {"no_id": true}}';
        });

        $result = nugget_revalidator::run($client, $this->config, ['n-bad', 'n-down']);

        $this->assertSame(['mode' => 'single', 'stored' => 0, 'missing' => 1, 'failed' => 1], $result);
        $this->assertNull(nugget_cache::get('n-bad', $this->config));
        $this->assertNotNull(nugget_cache::get('n-down', $this->config));
    }

    /**
     * No activity, no request.
     */
    public function test_no_ids_means_no_request(): void {
        $client = $this->client(function (): string {
            $this->fail('No request expected.');
        });

        $result = nugget_revalidator::run($client, $this->config);
        $this->assertSame(['mode' => 'batch', 'stored' => 0, 'missing' => 0, 'failed' => 0], $result);
    }

    /**
     * The nightly summary is printed only when something was revalidated.
     */
    public function test_refresh_documents_reports_in_the_cron_log(): void {
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('naas', ['course' => $course->id, 'nugget_id' => 'n-1']);
        $client = $this->client(function (string $service, ?array $params): string {
            return self::listing(array_map([self::class, 'document'], $params['nugget_ids']));
        });

        $this->expectOutputString("mod_naas: revalidated 1 Nugget documents (batch), 0 missing, 0 failed.\n");
        $this->assertSame(1, nugget_revalidator::refresh_documents($client, $this->config, true)['stored']);
        // Without the trace, nothing is printed.
        nugget_revalidator::refresh_documents($client, $this->config, false);
    }

    /**
     * A site without activities prints nothing in the cron log.
     */
    public function test_refresh_documents_is_silent_with_nothing_to_do(): void {
        $client = $this->client(function (): string {
            $this->fail('No request expected.');
        });

        $this->expectOutputString('');
        $this->assertSame('batch', nugget_revalidator::refresh_documents($client, $this->config, true)['mode']);
    }
}
