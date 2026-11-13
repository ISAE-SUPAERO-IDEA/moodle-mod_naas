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
 * Tests for {@see \mod_naas\cache_refresh} with the real refresh task and a simulated NaaS.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\cache_refresh::class)]
/**
 * The "Refresh cache" button: rebuild through the refresh task, then purge and re-warm.
 *
 * NaaS answers are queued with curl::mock_response(), which serves the last queued first.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_naas\cache_refresh
 */
final class cache_refresh_rebuild_test extends \advanced_testcase {
    /**
     * Configure a NaaS connection, refreshing a single search.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        set_config('naas_endpoint', 'https://naas.example.test/api', 'naas');
        set_config('naas_username', 'refresh-user', 'naas');
        set_config('naas_structure_id', 'struct-ours', 'naas');
        set_config('naas_refresh_limit', 1, 'naas');
    }

    /**
     * Drop the NaaS answers a failing test did not consume.
     */
    protected function tearDown(): void {
        $mocks = new \ReflectionProperty(\curl::class, 'mockresponses');
        $mocks->setAccessible(true);
        $mocks->setValue(null, []);
        parent::tearDown();
    }

    /**
     * Queue the answers of a catalogue rebuild, then the given person catalogue answer.
     *
     * @param string|object $persons Body answered to GET /persons, or an object that fails when read.
     * @return void
     */
    private function queue_rebuild($persons): void {
        $bodies = [
            json_encode(['payload' => ['items' => [['nugget_id' => 'landing']], 'results_count' => 1]]),
            json_encode(['payload' => ['items' => [['structure_id' => 'struct-a', 'name' => 'Alpha']], 'pages' => 1]]),
            json_encode(['payload' => ['items' => [['nugget_id' => 'seeded']], 'results_count' => 1]]),
            $persons,
        ];
        foreach (array_reverse($bodies) as $body) {
            \curl::mock_response($body);
        }
    }

    /**
     * Seed the caches that a successful refresh purges.
     *
     * @return void
     */
    private function seed_purged_caches(): void {
        \cache::make('mod_naas', vocabulary_lookup::CACHE_AREA)->set('seeded', 'name');
        \cache::make('mod_naas', nugget_cache::CACHE_AREA)->set('seeded', 'document');
    }

    /**
     * A full refresh rebuilds catalogue and searches, purges names and documents, and re-warms persons.
     */
    public function test_full_rebuilds_with_the_refresh_task(): void {
        $this->seed_purged_caches();
        $this->queue_rebuild(json_encode(['payload' => [
            'items' => [['email' => 'ada@example.test', 'firstname' => 'Ada', 'lastname' => 'Lovelace']],
            'pages' => 1,
        ]]));
        $started = time();

        $this->assertSame(get_string('cache_refresh_success', 'naas'), cache_refresh::full());

        $snapshot = catalogue_cache::get();
        $this->assertGreaterThanOrEqual($started, $snapshot['warmed_at']);
        $this->assertSame(['landing'], array_column($snapshot['search']['items'], 'nugget_id'));
        $this->assertCount(1, search_cache::index());
        $vocabulary = \cache::make('mod_naas', vocabulary_lookup::CACHE_AREA);
        $this->assertFalse($vocabulary->get('seeded'));
        $this->assertFalse(\cache::make('mod_naas', nugget_cache::CACHE_AREA)->get('seeded'));
        $this->assertStringContainsString('Lovelace', (string) $vocabulary->get('person_catalog_v1'));
    }

    /**
     * A broken person catalogue answer is only logged: the refresh still succeeds.
     */
    public function test_full_logs_person_catalogue_failure(): void {
        $this->seed_purged_caches();
        // An unexpected (non-Moodle) error while the person catalogue answer is read.
        $this->queue_rebuild(new class {
            /**
             * Reading the body fails.
             *
             * @return string
             */
            public function __toString(): string {
                throw new \RuntimeException('NaaS connection reset');
            }
        });

        $this->assertSame(get_string('cache_refresh_success', 'naas'), cache_refresh::full());

        $messages = $this->getDebuggingMessages();
        $this->assertCount(1, $messages);
        $this->assertSame('NAAS person catalog refresh failed: NaaS connection reset', $messages[0]->message);
        $this->resetDebugging();
        $this->assertFalse(\cache::make('mod_naas', vocabulary_lookup::CACHE_AREA)->get('seeded'));
        $this->assertNotNull(catalogue_cache::get());
    }
}
