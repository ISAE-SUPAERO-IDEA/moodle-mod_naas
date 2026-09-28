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

if ($hassiteconfig) {
    // About.
    $settings->add(new admin_setting_heading(
        'naas/heading_about',
        get_string('naas_settings_about', 'naas'),
        get_string('naas_settings_about_information', 'naas')
    ));

    // Connection.
    $settings->add(new admin_setting_heading(
        'naas/heading_connection',
        get_string('naas_settings_connection', 'naas'),
        get_string('naas_settings_information', 'naas')
    ));

    $settings->add(new admin_setting_configtext(
        'naas/naas_endpoint',
        get_string('naas_settings_endpoint', 'naas'),
        get_string('naas_settings_endpoint_help', 'naas'),
        'https://api.naas-edu.eu/api',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'naas/naas_username',
        get_string('naas_settings_username', 'naas'),
        get_string('naas_settings_username_help', 'naas'),
        'structures_06d37c13-6ffe-4c4a-a9e3-ac227652f98c_learner',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'naas/naas_password',
        get_string('naas_settings_password', 'naas'),
        get_string('naas_settings_password_help', 'naas'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'naas/naas_structure_id',
        get_string('naas_settings_structure_id', 'naas'),
        get_string('naas_settings_structure_id_help', 'naas'),
        '06d37c13-6ffe-4c4a-a9e3-ac227652f98c',
        PARAM_TEXT
    ));

    $settings->add(new \mod_naas\admin\test_connection_setting(
        'naas/test_connection_ui',
        get_string('test_connection', 'naas'),
        get_string('test_connection_information', 'naas')
    ));

    // Cache.
    $settings->add(new admin_setting_heading(
        'naas/heading_cache',
        get_string('naas_settings_cache', 'naas'),
        get_string('naas_settings_cache_information', 'naas')
    ));
    $settings->add(new \mod_naas\admin\refresh_cache_setting(
        'naas/refresh_cache_ui',
        get_string('cache_refresh', 'naas'),
        get_string('cache_refresh_information', 'naas')
    ));

    // Privacy.
    $settings->add(new admin_setting_heading(
        'naas/heading_privacy',
        get_string('naas_settings_privacy', 'naas'),
        get_string('naas_settings_privacy_information', 'naas')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'naas/naas_privacy_learner_mail',
        get_string('naas_settings_privacy_learner_mail', 'naas'),
        get_string('naas_settings_privacy_learner_mail_help', 'naas'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'naas/naas_privacy_learner_name',
        get_string('naas_settings_privacy_learner_name', 'naas'),
        get_string('naas_settings_privacy_learner_name_help', 'naas'),
        1
    ));

    // Learner experience.
    $settings->add(new admin_setting_heading(
        'naas/heading_learner',
        get_string('naas_settings_learner', 'naas'),
        get_string('naas_settings_learner_information', 'naas')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'naas/naas_feedback',
        get_string('naas_settings_feedback', 'naas'),
        get_string('naas_settings_feedback_help', 'naas'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'naas/naas_nugbot',
        get_string('naas_settings_nugbot', 'naas'),
        get_string('naas_settings_nugbot_help', 'naas'),
        0
    ));

    // Catalogue.
    $settings->add(new admin_setting_heading(
        'naas/heading_catalogue',
        get_string('naas_settings_catalogue', 'naas'),
        get_string('naas_settings_catalogue_information', 'naas')
    ));

    $settings->add(new admin_setting_configselect(
        'naas/naas_license_filter',
        get_string('naas_settings_license_filter', 'naas'),
        get_string('naas_settings_license_filter_help', 'naas'),
        \mod_naas\catalogue_filters::LICENSE_ALL,
        [
            \mod_naas\catalogue_filters::LICENSE_ALL =>
                get_string('naas_settings_license_filter_all', 'naas'),
            \mod_naas\catalogue_filters::LICENSE_COMMERCIAL =>
                get_string('naas_settings_license_filter_commercial', 'naas'),
            \mod_naas\catalogue_filters::LICENSE_NONCOMMERCIAL =>
                get_string('naas_settings_license_filter_noncommercial', 'naas'),
        ]
    ));

    $settings->add(new admin_setting_configselect(
        'naas/naas_access_filter',
        get_string('naas_settings_access_filter', 'naas'),
        get_string('naas_settings_access_filter_help', 'naas'),
        \mod_naas\catalogue_filters::ACCESS_ALL,
        [
            \mod_naas\catalogue_filters::ACCESS_ALL =>
                get_string('naas_settings_access_filter_all', 'naas'),
            \mod_naas\catalogue_filters::ACCESS_UNRESTRICTED =>
                get_string('naas_settings_access_filter_unrestricted', 'naas'),
            \mod_naas\catalogue_filters::ACCESS_RESTRICTED =>
                get_string('naas_settings_access_filter_restricted', 'naas'),
        ]
    ));

    $settings->add(new admin_setting_configtextarea(
        'naas/naas_filter',
        get_string('naas_settings_filter', 'naas'),
        get_string('naas_settings_filter_help', 'naas'),
        '',
        PARAM_TEXT
    ));

    // Appearance.
    $settings->add(new admin_setting_heading(
        'naas/heading_appearance',
        get_string('naas_settings_appearance', 'naas'),
        get_string('naas_settings_appearance_information', 'naas')
    ));

    $settings->add(new admin_setting_configtextarea(
        'naas/naas_css',
        get_string('naas_settings_css', 'naas'),
        get_string('naas_settings_css_help', 'naas'),
        '',
        PARAM_TEXT
    ));

    // Advanced.
    $advancedinfo = get_string('naas_settings_advanced_information', 'naas');
    if (!empty(get_config('naas', 'naas_ssl_noverify'))) {
        $advancedinfo .= "\n\n" . get_string('naas_settings_ssl_noverify_active', 'naas');
    }
    $settings->add(new admin_setting_heading(
        'naas/heading_advanced',
        get_string('naas_settings_advanced', 'naas'),
        $advancedinfo
    ));

    $settings->add(new admin_setting_configtext(
        'naas/naas_timeout',
        get_string('naas_settings_timeout', 'naas'),
        get_string('naas_settings_timeout_help', 'naas'),
        10,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'naas/naas_ssl_noverify',
        get_string('naas_settings_ssl_noverify', 'naas'),
        get_string('naas_settings_ssl_noverify_help', 'naas'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'naas/naas_refresh_limit',
        get_string('naas_refresh_limit', 'naas'),
        get_string('naas_refresh_limit_desc', 'naas', \mod_naas\search_cache::MAX_ENTRIES),
        \mod_naas\task\refresh_catalogue::DEFAULT_LIMIT,
        PARAM_INT
    ));
}
