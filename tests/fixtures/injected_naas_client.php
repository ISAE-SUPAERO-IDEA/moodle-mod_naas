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
 * Client injection for test subclasses of the NaaS web services.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\external;

/**
 * Replaces make_naas_client() with the client given to using().
 *
 * A web service test declares `new class extends <web service> { use injected_naas_client; }`
 * and calls execute() on the class name using() returns, so no HTTP request is sent.
 * Each class using the trait has its own static property: the subclasses never share a client.
 */
trait injected_naas_client {
    /** @var \mod_naas\naas_client|null Client returned instead of a real one. */
    public static $naasinjection = null;

    /**
     * Inject the NaaS client the next calls will use.
     *
     * @param \mod_naas\naas_client $client
     * @return string Name of the subclass, to call execute() on.
     */
    public static function using(\mod_naas\naas_client $client): string {
        self::$naasinjection = $client;
        return static::class;
    }

    /**
     * Return the injected NaaS client, or a real one when none was injected.
     *
     * @param object $config
     * @return \mod_naas\naas_client
     */
    protected static function make_naas_client(object $config): \mod_naas\naas_client {
        return self::$naasinjection ?? parent::make_naas_client($config);
    }
}
