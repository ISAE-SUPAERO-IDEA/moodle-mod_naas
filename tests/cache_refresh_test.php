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
 * Tests for {@see \mod_naas\cache_refresh}.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

use advanced_testcase;
use mod_naas\cache_refresh;
use mod_naas\catalogue_cache;
use mod_naas\nugget_cache;
use mod_naas\vocabulary_lookup;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\cache_refresh::class)]
/**
 * Tests for the admin cache purge and rebuild.
 *
 * @covers \mod_naas\cache_refresh
 */
final class cache_refresh_test extends advanced_testcase {
    /**
     * A click with no saved API URL must not wipe an existing catalogue.
     */
    public function test_full_keeps_cache_when_endpoint_missing(): void {
        global $CFG;

        $this->resetAfterTest(true);
        unset_config('naas_endpoint', 'naas');
        unset($CFG->naas_endpoint);

        catalogue_cache::store([
            'fingerprint' => 'kept',
            'producers' => [],
        ]);

        try {
            cache_refresh::full();
            $this->fail('Expected cache_refresh_failed');
        } catch (\moodle_exception $e) {
            $this->assertSame('cache_refresh_failed', $e->errorcode);
        }

        $snapshot = catalogue_cache::get();
        $this->assertIsArray($snapshot);
        $this->assertSame('kept', $snapshot['fingerprint']);
    }

    /**
     * Seed one entry in each cache that full() may purge.
     */
    private function seed_caches(): void {
        catalogue_cache::store(['fingerprint' => 'old', 'warmed_at' => time() - 3600, 'producers' => []]);
        \cache::make('mod_naas', vocabulary_lookup::CACHE_AREA)->set('seeded', 'name');
        \cache::make('mod_naas', nugget_cache::CACHE_AREA)->set('seeded', 'document');
    }

    /**
     * A cache_refresh whose rebuild either writes a fresh snapshot or, like an
     * unreachable NaaS, writes nothing.
     *
     * @param bool $naasanswers
     * @return string Class name to call statically.
     */
    private function refresh_with(bool $naasanswers): string {
        $refresh = new class extends cache_refresh {
            /** @var bool */
            public static $naasanswers = false;

            /**
             * Rebuild stub.
             *
             * @return \mod_naas\task\refresh_catalogue
             */
            protected static function make_refresh_task(): \mod_naas\task\refresh_catalogue {
                $answers = self::$naasanswers;
                return new class ($answers) extends \mod_naas\task\refresh_catalogue {
                    /** @var bool */
                    private $answers;

                    /**
                     * Constructor.
                     *
                     * @param bool $answers
                     */
                    public function __construct(bool $answers) {
                        $this->answers = $answers;
                    }

                    /**
                     * Store a fresh snapshot only when NaaS "answers".
                     *
                     * @param bool $trace
                     * @return int|null
                     */
                    public function refresh(bool $trace = false): ?int {
                        if ($this->answers) {
                            catalogue_cache::store(['fingerprint' => 'new', 'warmed_at' => time(), 'producers' => []]);
                        }
                        return 0;
                    }
                };
            }
        };
        $class = get_class($refresh);
        $class::$naasanswers = $naasanswers;
        return $class;
    }

    /**
     * NaaS unreachable: the old catalogue, names and Nugget documents all stay.
     */
    public function test_full_keeps_every_cache_when_naas_does_not_answer(): void {
        $this->resetAfterTest(true);
        set_config('naas_endpoint', 'https://api.example.test/api', 'naas');
        $this->seed_caches();
        $refresh = $this->refresh_with(false);

        try {
            $refresh::full();
            $this->fail('Expected cache_refresh_failed');
        } catch (\moodle_exception $e) {
            $this->assertSame('cache_refresh_failed', $e->errorcode);
        }

        $this->assertSame('old', catalogue_cache::get()['fingerprint']);
        $this->assertSame('name', \cache::make('mod_naas', vocabulary_lookup::CACHE_AREA)->get('seeded'));
        $this->assertSame('document', \cache::make('mod_naas', nugget_cache::CACHE_AREA)->get('seeded'));
    }

    /**
     * Catalogue rebuilt: names and Nugget documents are dropped so they are fetched again.
     */
    public function test_full_purges_names_and_documents_after_a_successful_rebuild(): void {
        $this->resetAfterTest(true);
        // Unroutable endpoint: the person catalog warm-up fails fast and is only logged.
        set_config('naas_endpoint', 'http://127.0.0.1:9/api', 'naas');
        $this->seed_caches();
        $refresh = $this->refresh_with(true);

        $this->assertSame(get_string('cache_refresh_success', 'naas'), $refresh::full());

        $this->assertSame('new', catalogue_cache::get()['fingerprint']);
        $this->assertFalse(\cache::make('mod_naas', vocabulary_lookup::CACHE_AREA)->get('seeded'));
        $this->assertFalse(\cache::make('mod_naas', nugget_cache::CACHE_AREA)->get('seeded'));
        $this->resetDebugging();
    }
}
