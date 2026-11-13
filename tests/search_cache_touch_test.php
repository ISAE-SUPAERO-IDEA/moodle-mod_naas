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
 * Tests for the read tracking of the search cache.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\search_cache::class)]
/**
 * Tests for search_cache::touch() and the canonical form of list options.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_naas\search_cache
 */
final class search_cache_touch_test extends \advanced_testcase {
    /** @var object Connection the entries belong to. */
    private $config;

    /**
     * Isolate config and caches, and store one search under the key 'k'.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->config = (object) ['naas_endpoint' => 'https://api.naas.example/api', 'naas_username' => 'u'];
        search_cache::purge();
        search_cache::store('k', ['page' => 0], '{"payload":{"items":[],"results_count":0}}', $this->config);
    }

    /**
     * Pretend the entry 'k' was last read at $at.
     *
     * @param int $at
     */
    private function last_read_at(int $at): void {
        $index = search_cache::index();
        $index['k']['last_read_at'] = $at;
        \cache::make('mod_naas', search_cache::CACHE_AREA)->set(search_cache::INDEX_KEY, json_encode($index));
    }

    /**
     * A key read more than an hour ago gets its read time updated.
     */
    public function test_touch_records_a_read_after_the_interval(): void {
        $this->last_read_at(time() - search_cache::TOUCH_INTERVAL - 10);

        search_cache::touch('k');

        $this->assertGreaterThanOrEqual(time() - 1, search_cache::index()['k']['last_read_at']);
    }

    /**
     * A key read within the hour is not rewritten.
     */
    public function test_touch_skips_a_recent_read(): void {
        $recent = time() - 60;
        $this->last_read_at($recent);

        search_cache::touch('k');

        $this->assertSame($recent, search_cache::index()['k']['last_read_at']);
    }

    /**
     * A key missing from the index is ignored.
     */
    public function test_touch_ignores_an_unknown_key(): void {
        $before = search_cache::index();

        search_cache::touch('unknown');

        $this->assertSame($before, search_cache::index());
        $this->assertArrayNotHasKey('unknown', $before);
    }

    /**
     * A key evicted while the touch waited for the lock is not recreated.
     */
    public function test_touch_does_not_recreate_a_key_evicted_meanwhile(): void {
        $this->last_read_at(time() - search_cache::TOUCH_INTERVAL - 10);
        $racing = new class extends search_cache {
            /**
             * Evict 'k' just before the lock is handed out, as a parallel request would.
             *
             * @return \core\lock\lock|false
             */
            protected static function index_lock() {
                $index = search_cache::index();
                unset($index['k']);
                \cache::make('mod_naas', search_cache::CACHE_AREA)->set(search_cache::INDEX_KEY, json_encode($index));
                return parent::index_lock();
            }
        };

        $racing::touch('k');

        $this->assertSame([], search_cache::index());
    }

    /**
     * List options with only blank values are dropped from the canonical query.
     */
    public function test_canonical_query_drops_lists_of_blank_values(): void {
        $query = search_cache::canonical_query([
            'tags' => ['', null],
            'producers' => ['managed_by:structure:isae', 'b'],
        ]);

        $this->assertSame('{"page":"0","producers":["b","isae"]}', $query);
    }
}
