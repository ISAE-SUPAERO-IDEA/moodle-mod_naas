<?php
// This file is part of Moodle - http://moodle.org
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
 * Plugin Nugget API
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */
defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_naas_test_config' => [
        'classname' => 'mod_naas\external\proxy_naas_api',
        'methodname' => 'test_config',
        'description' => 'Test the plugin configuration',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/naas:admin',
    ],
    'mod_naas_get_nugget' => [
        'classname' => 'mod_naas\external\proxy_naas_api',
        'methodname' => 'get_nugget',
        'description' => 'Get a specific nugget',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/naas:addinstance',
    ],
    'mod_naas_view_nugget' => [
        'classname' => 'mod_naas\external\proxy_naas_api',
        'methodname' => 'view_nugget',
        'description' => 'View a specific nugget',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/naas:view',
    ],
    'mod_naas_get_nugget_preview' => [
        'classname' => 'mod_naas\external\proxy_naas_api',
        'methodname' => 'get_nugget_preview',
        'description' => 'Get preview URL for a nugget version',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/naas:addinstance',
    ],
    'mod_naas_get_domain' => [
        'classname' => 'mod_naas\external\proxy_naas_api',
        'methodname' => 'get_domain',
        'description' => 'Get domain information',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/naas:view',
    ],
    'mod_naas_get_structure' => [
        'classname' => 'mod_naas\external\proxy_naas_api',
        'methodname' => 'get_structure',
        'description' => 'Get structure information',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/naas:view',
    ],
    'mod_naas_get_person' => [
        'classname' => 'mod_naas\external\proxy_naas_api',
        'methodname' => 'get_person',
        'description' => 'Get person information',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/naas:view',
    ],
    'mod_naas_search_nuggets' => [
        'classname' => 'mod_naas\external\proxy_naas_api',
        'methodname' => 'search_nuggets',
        'description' => 'Search for nuggets',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/naas:addinstance',
    ],
];

$services = [
    'NAAS Service' => [
        'functions' => array_keys($functions),
        'restrictedusers' => 0,
        'enabled' => 1,
    ],
];
