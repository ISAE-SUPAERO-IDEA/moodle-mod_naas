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
 * NAAS module upgrade code
 *
 * This file keeps track of upgrades to the naas module.
 *
 * Sometimes, changes between versions involve alterations to database
 * structures and other major things that may break installations.
 *
 * The upgrade function in this file will attempt to perform all the
 * necessary actions to upgrade your older installation to the current version.
 *
 * The commands in here will all be database-neutral, using the methods of
 * the database_manager class.
 *
 * Please do not forget to use upgrade_set_timeout() before any action that
 * may take longer time to finish.
 *
 * @param int $oldversion
 * @return bool
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */
function xmldb_naas_upgrade($oldversion) {
    global $CFG, $DB;
    $dbman = $DB->get_manager();
    if ($oldversion < 2023090704) {
        $table = new xmldb_table('naas');
        $field = new xmldb_field('cgu_agreement', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'nugget_id');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Naas savepoint reached.
        upgrade_mod_savepoint(true, 2023090704, 'naas');
    }
    if ($oldversion < 2024061403) {
        $table = new xmldb_table('naas_activity_outcome');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('user_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('activity_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('sourced_id', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('date_added', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $dbman->create_table($table);
        }
        // Naas savepoint reached.
        upgrade_mod_savepoint(true, 2024061403, 'naas');
    }
    if ($oldversion < 2026102700) {
        $table = new xmldb_table('naas');
        // Fields to create if missing, or to align with install.xml if they already exist.
        $fields = [
            new xmldb_field('completionpass', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'nugget_id'),
            new xmldb_field('completionattemptsexhausted', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'nugget_id'),
            new xmldb_field('completionminattempts', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'nugget_id'),
            new xmldb_field('grade_method', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1', 'nugget_id'),
            new xmldb_field('attempts', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'nugget_id'),
            new xmldb_field('allowofflineattempts', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'attempts'),
            new xmldb_field('cgu_agreement', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'nugget_id'),
        ];
        // Replace NULL values before switching to NOT NULL (only on columns that already exist).
        $defaults = [
            'grade_method' => 1,
            'completionpass' => 0,
            'completionattemptsexhausted' => 0,
            'completionminattempts' => 0,
        ];
        foreach ($defaults as $name => $value) {
            if ($dbman->field_exists($table, new xmldb_field($name))) {
                $DB->execute("UPDATE {naas} SET $name = :val WHERE $name IS NULL", ['val' => $value]);
            }
        }
        foreach ($fields as $field) {
            if ($dbman->field_exists($table, $field)) {
                $dbman->change_field_notnull($table, $field);
                $dbman->change_field_default($table, $field);
            } else {
                $dbman->add_field($table, $field);
            }
        }
        // Table naas_activity_outcome: set default values to 0.
        $table = new xmldb_table('naas_activity_outcome');
        foreach (['user_id', 'activity_id', 'date_added'] as $name) {
            $field = new xmldb_field($name, XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            if ($dbman->field_exists($table, $field)) {
                $dbman->change_field_default($table, $field);
            }
        }
        // Naas savepoint reached.
        upgrade_mod_savepoint(true, 2026102700, 'naas');
    }
    return true;
}
