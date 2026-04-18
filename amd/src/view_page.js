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
 * Wiring for the mod_naas view page interactions.
 *
 * @module     mod_naas/view_page
 * @copyright  2019 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('mod_naas/view_page', [], function() {
    return {
        /**
         * Initialize the view page DOM interactions.
         */
        init: function() {
            let about_button = document.querySelector('.secondary-navigation nav ul li[data-key=about]');
            if (about_button) {
                let widget = document.querySelector('#nugget-info-button div a');
                about_button.onclick = function() {
                    if (widget) {
                        widget.click();
                    }
                };
            }
        }
    };
});
