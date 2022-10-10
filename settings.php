<?php
/**
 * MOD Naas: Settings
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // -------------------- NaaS settings --------------------.
    $settings->add(new admin_setting_heading(
        'naas',
        get_string('naas_settings', 'naas'),
        get_string('naas_settings_help', 'naas') 
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

