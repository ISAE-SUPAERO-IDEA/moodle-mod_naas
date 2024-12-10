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
 * @package mod_nugget
 */

defined('MOODLE_INTERNAL') || die();
require_once('privacy/provider.php');

if ($hassiteconfig) {
    // NaaS settings.
    $settings->add(new admin_setting_heading(
        'naas',
        get_string('naas_settings', 'nugget'),
        get_string('naas_settings_information', 'nugget')
    ));

    $settings->add(new admin_setting_configtext(
        'naas/naas_endpoint',
        new lang_string('naas_settings_endpoint', 'nugget'),
        new lang_string('naas_settings_endpoint_help', 'nugget'),
        'https://api.naas-edu.eu/api',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'naas/naas_username',
        new lang_string('naas_settings_username', 'nugget'),
        new lang_string('naas_settings_username_help', 'nugget'),
        'structures_06d37c13-6ffe-4c4a-a9e3-ac227652f98c_learner',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'naas/naas_structure_id',
        new lang_string('naas_settings_structure_id', 'nugget'),
        new lang_string('naas_settings_structure_id_help', 'nugget'),
        '06d37c13-6ffe-4c4a-a9e3-ac227652f98c',
         PARAM_TEXT
     ));

    $settings->add(new admin_setting_configpasswordunmask(
        'naas/naas_password',
        new lang_string('naas_settings_password', 'nugget'),
        new lang_string('naas_settings_password_help', 'nugget'),
        'h6teLq3cQangBLFE6qw8',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'naas/naas_timeout',
        new lang_string('naas_settings_timeout', 'nugget'),
        new lang_string('naas_settings_timeout_help', 'nugget'),
        10,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtextarea(
        'naas/naas_css',
        new lang_string('naas_settings_css', 'nugget'),
        new lang_string('naas_settings_css_help', 'nugget'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtextarea(
        'naas/naas_filter',
        new lang_string('naas_settings_filter', 'nugget'),
        new lang_string('naas_settings_filter_help', 'nugget'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'naas/naas_feedback',
        new lang_string('naas_settings_feedback', 'nugget'),
        new lang_string('naas_settings_feedback_help', 'nugget'),
        1
    ));

    $settings->add(new admin_setting_heading(
        'naas/privacy',
        get_string('naas_settings_privacy', 'nugget'),
        (new provider())->get_reason()
    ));

    $settings->add(new admin_setting_configcheckbox(
        'naas/naas_privacy_learner_mail',
        new lang_string('naas_settings_privacy_learner_mail', 'nugget'),
        new lang_string('naas_settings_privacy_learner_mail_help', 'nugget'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'naas/naas_privacy_learner_name',
        new lang_string('naas_settings_privacy_learner_name', 'nugget'),
        new lang_string('naas_settings_privacy_learner_name_help', 'nugget'),
        1
    ));
}
