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
 * Fake NaaS for Behat: answers the plugin's requests from naas_catalogue.json.
 *
 * Loaded only by naas_client::create() on a Behat test site.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

defined('MOODLE_INTERNAL') || die();

/**
 * A NaaS client that never leaves the test site.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_naas_client extends naas_client {
    /** @var array|null Fixture catalogue, read once. */
    private static $catalogue = null;

    /**
     * Answer a NaaS request from the fixture catalogue.
     *
     * @param string $protocol
     * @param string $service Path, possibly with a query string.
     * @param mixed $data
     * @param array|null $params
     * @return string JSON body
     * @throws \moodle_exception error:naas_api:not_found for anything the fixtures do not know.
     */
    public function request_raw($protocol, $service, $data = null, $params = null): string {
        $path = (string) parse_url($service, PHP_URL_PATH);
        $query = [];
        parse_str((string) parse_url($service, PHP_URL_QUERY), $query);
        $query += (array) $params;
        $catalogue = self::catalogue();

        if ($protocol === 'POST' && preg_match('#^/versions/[^/]+/records/[a-z]+$#', $path)) {
            return self::payload(['statusCode' => 202, 'statusMessage' => 'Accepted']);
        }
        if ($path === '' || $path === '/') {
            return self::payload(['name' => 'NaaS Behat fixture', 'version' => 'behat']);
        }
        if ($path === '/auth') {
            return self::payload(['login' => 'behat', 'name' => 'Behat']);
        }
        if ($path === '/nuggets/search') {
            return self::payload(self::search($catalogue['nuggets'], $query));
        }
        if (preg_match('#^/nuggets/([^/]+)/(default_version|lti)$#', $path, $m)) {
            $nugget = self::find_nugget($catalogue['nuggets'], $m[1]);
            if ($m[2] === 'lti') {
                return self::payload(['url' => 'https://naas.behat.test/lti', 'key' => 'behat-key', 'secret' => 'behat-secret']);
            }
            return self::payload($nugget);
        }
        if (preg_match('#^/versions/([^/]+)/preview_url$#', $path, $m)) {
            return self::payload('https://naas.behat.test/preview/' . rawurlencode($m[1]));
        }
        if (preg_match('#^/vocabularies/nugget_domains_vocabulary/(.+)$#', $path, $m)) {
            return self::payload(self::known($catalogue['domains'], rawurldecode($m[1])));
        }
        if (preg_match('#^/structures/(?!search$)([^/]+)$#', $path, $m)) {
            return self::payload(self::known($catalogue['structures'], rawurldecode($m[1])));
        }
        if ($path === '/structures' || $path === '/structures/search') {
            return self::payload(self::listing(array_values($catalogue['structures'])));
        }
        if (preg_match('#^/persons/(?!search$)([^/]+)$#', $path, $m)) {
            return self::payload(self::known($catalogue['persons'], rawurldecode($m[1])));
        }
        if ($path === '/persons' || $path === '/persons/search') {
            return self::payload(self::listing(array_values($catalogue['persons'])));
        }
        throw new \moodle_exception('error:naas_api:not_found', 'naas');
    }

    /**
     * Search the fixture nuggets: keyword on the name, then the facet filters.
     *
     * @param array $nuggets
     * @param array $query
     * @return array NaaS search body
     */
    private static function search(array $nuggets, array $query): array {
        $fulltext = trim((string) ($query['fulltext'] ?? ''));
        $items = array_values(array_filter($nuggets, function (array $nugget) use ($fulltext, $query): bool {
            if ($fulltext !== '' && stripos($nugget['name'], $fulltext) === false) {
                return false;
            }
            foreach (['producers', 'authors', 'related_domains', 'language', 'level', 'type'] as $facet) {
                if (!isset($query[$facet]) || $query[$facet] === '' || $query[$facet] === []) {
                    continue;
                }
                $wanted = (array) $query[$facet];
                $values = (array) ($nugget[$facet] ?? []);
                if (!array_intersect($wanted, $values)) {
                    return false;
                }
            }
            return true;
        }));
        $aggregations = [];
        foreach (['producers', 'authors', 'related_domains', 'language', 'level', 'type'] as $facet) {
            $counts = [];
            foreach ($items as $nugget) {
                foreach ((array) ($nugget[$facet] ?? []) as $value) {
                    $counts[$value] = ($counts[$value] ?? 0) + 1;
                }
            }
            $buckets = [];
            foreach ($counts as $key => $count) {
                $buckets[] = ['key' => (string) $key, 'docCount' => $count];
            }
            $aggregations[$facet] = ['buckets' => $buckets];
        }
        $size = max(1, (int) ($query['page_size'] ?? 9));
        $page = max(0, (int) ($query['page'] ?? 0));
        return [
            'items' => array_slice($items, $page * $size, $size),
            'aggregations' => $aggregations,
            'results_count' => count($items),
        ];
    }

    /**
     * A nugget by id, or "not found".
     *
     * @param array $nuggets
     * @param string $id
     * @return array
     */
    private static function find_nugget(array $nuggets, string $id): array {
        foreach ($nuggets as $nugget) {
            if ($nugget['nugget_id'] === $id) {
                return $nugget;
            }
        }
        throw new \moodle_exception('error:naas_api:not_found', 'naas');
    }

    /**
     * An entry of a fixture map, or "not found".
     *
     * @param array $map
     * @param string $key
     * @return array
     */
    private static function known(array $map, string $key): array {
        if (!isset($map[$key])) {
            throw new \moodle_exception('error:naas_api:not_found', 'naas');
        }
        return $map[$key];
    }

    /**
     * A NaaS listing body on a single page.
     *
     * @param array $items
     * @return array
     */
    private static function listing(array $items): array {
        return ['items' => $items, 'results_count' => count($items), 'page_count' => 1];
    }

    /**
     * Wrap a value in the NaaS response envelope.
     *
     * @param mixed $value
     * @return string
     */
    private static function payload($value): string {
        return json_encode(['payload' => $value], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * The fixture catalogue.
     *
     * @return array
     */
    private static function catalogue(): array {
        if (self::$catalogue === null) {
            self::$catalogue = json_decode((string) file_get_contents(__DIR__ . '/naas_catalogue.json'), true);
        }
        return self::$catalogue;
    }
}
