<?php
/**
 * Moodle Nugget Plugin : Privacy API
 *
 * @package    mod_naas
 * @copyright  2023 onwards ISAE-SUPAERO
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class provider
	// This plugin export data to an external location
	{

	/**
     * Get the language string identifier with the component's language
     * file to explains why the plugin needs to collect personal data and where it is stored.
     *
     * @return  string
     */
	public static function get_reason() : string {
		return get_string('naas_settings_privacy_information', 'naas');
	}
}
