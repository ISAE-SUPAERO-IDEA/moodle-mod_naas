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
 * URL module upgrade code
 *
 * This file keeps track of upgrades to
 * the resource module
 *
 * Sometimes, changes between versions involve
 * alterations to database structures and other
 * major things that may break installations.
 *
 * The upgrade function in this file will attempt
 * to perform all the necessary actions to upgrade
 * your older installation to the current version.
 *
 * If there's something it cannot do itself, it
 * will tell you what you need to do.
 *
 * The commands in here will all be database-neutral,
 * using the methods of database_manager class
 *
 * Please do not forget to use upgrade_set_timeout()
 * before any action that may take longer time to finish.
 * @param string $oldversion
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */
function xmldb_naas_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();
    // Automatically generated Moodle v3.3.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.4.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.5.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.6.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.7.0 release upgrade line.
    // Put any upgrade step following this.
    debugging("upgrade", DEBUG_DEVELOPER);
    if ($oldversion < 2023090704) {
        debugging("cgu", DEBUG_DEVELOPER);

        $table = new xmldb_table('naas');
        $field = new xmldb_field('cgu_agreement', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'nugget_id');

        if (!$dbman->field_exists($table, $field)) {
            debugging("c", DEBUG_DEVELOPER);
            $dbman->add_field($table, $field);
        }

        // Naas savepoint reached.
        upgrade_mod_savepoint(true, 2023090704, 'naas');
    }

    if ($oldversion < 2024061403) {
        debugging("retour lti", DEBUG_DEVELOPER);

        $table = new xmldb_table('naas_activity_outcome');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('user_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('activity_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('sourced_id', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('date_added', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2024061403, 'naas');
    }

    $table = new xmldb_table('naas');

    $completionpass = new xmldb_field('completionpass', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'nugget_id');
    if (!$dbman->field_exists($table, $completionpass)) {
        $dbman->add_field($table, $completionpass);
    }
    $completionattemptsexhausted = new xmldb_field(
        'completionattemptsexhausted',
        XMLDB_TYPE_INTEGER,
        '10',
        null,
        null,
        null,
        null,
        'nugget_id'
    );
    if (!$dbman->field_exists($table, $completionattemptsexhausted)) {
        $dbman->add_field($table, $completionattemptsexhausted);
    }
    $completionminattempts = new xmldb_field(
        'completionminattempts',
        XMLDB_TYPE_INTEGER,
        '10',
        null,
        null,
        null,
        null,
        'nugget_id'
    );
    if (!$dbman->field_exists($table, $completionminattempts)) {
        $dbman->add_field($table, $completionminattempts);
    }
    $grademethod = new xmldb_field('grade_method', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'nugget_id');
    if (!$dbman->field_exists($table, $grademethod)) {
        $dbman->add_field($table, $grademethod);
    }

    return true;
}
