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
 * Moodle Nugget Plugin : Settings
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */

defined('MOODLE_INTERNAL') || die();
require_once('privacy/provider.php');

if ($hassiteconfig) {
    // -------------------- NaaS settings --------------------.
    $settings->add(new admin_setting_heading(
        'naas',
        get_string('naas_settings', 'naas'),
        get_string('naas_settings_information', 'naas')
    ));
    // Endpoint
    $settings->add(new admin_setting_configtext(
        'naas/naas_endpoint',
        new lang_string('naas_settings_endpoint', 'naas'),
        new lang_string('naas_settings_endpoint_help', 'naas'),
        'https://api.naas-edu.eu/api',
        PARAM_URL
    ));
    // User
    $settings->add(new admin_setting_configtext(
        'naas/naas_username',
        new lang_string('naas_settings_username', 'naas'),
        new lang_string('naas_settings_username_help', 'naas'),
        'structures_06d37c13-6ffe-4c4a-a9e3-ac227652f98c_learner',
        PARAM_TEXT
    ));
    // Structure
    $settings->add(new admin_setting_configtext(
        'naas/naas_structure_id',
        new lang_string('naas_settings_structure_id', 'naas'),
        new lang_string('naas_settings_structure_id_help', 'naas'),
        '06d37c13-6ffe-4c4a-a9e3-ac227652f98c',
         PARAM_TEXT
     ));
    // Password
    $settings->add(new admin_setting_configpasswordunmask(
        'naas/naas_password',
        new lang_string('naas_settings_password', 'naas'),
        new lang_string('naas_settings_password_help', 'naas'),
        'h6teLq3cQangBLFE6qw8',
        PARAM_TEXT
    ));
    // Timeout
    $settings->add(new admin_setting_configtext(
        'naas/naas_timeout',
        new lang_string('naas_settings_timeout', 'naas'),
        new lang_string('naas_settings_timeout_help', 'naas'),
        10,
        PARAM_INT
    ));
    // CSS
    $settings->add(new admin_setting_configtextarea(
        'naas/naas_css',
        new lang_string('naas_settings_css', 'naas'),
        new lang_string('naas_settings_css_help', 'naas'),
        '',
        PARAM_TEXT
    ));
    // Filter
    $settings->add(new admin_setting_configtextarea(
        'naas/naas_filter',
        new lang_string('naas_settings_filter', 'naas'),
        new lang_string('naas_settings_filter_help', 'naas'),
        '',
        PARAM_TEXT
    ));
    // Feedback
    $settings->add(new admin_setting_configcheckbox(
        'naas/naas_feedback',
        new lang_string('naas_settings_feedback', 'naas'),
        new lang_string('naas_settings_feedback_help', 'naas'),
        1
    ));

    // -------------------- NaaS Privacy --------------------.
    $settings->add(new admin_setting_heading(
        'naas/privacy',
        get_string('naas_settings_privacy', 'naas'),
        (new provider())->get_reason()
    ));
    // Privacy mail
    $settings->add(new admin_setting_configcheckbox(
        'naas/naas_privacy_learner_mail',
        new lang_string('naas_settings_privacy_learner_mail', 'naas'),
        new lang_string('naas_settings_privacy_learner_mail_help', 'naas'),
        1
    ));
    // Privacy name
    $settings->add(new admin_setting_configcheckbox(
        'naas/naas_privacy_learner_name',
        new lang_string('naas_settings_privacy_learner_name', 'naas'),
        new lang_string('naas_settings_privacy_learner_name_help', 'naas'),
        1
    ));
}
