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
 * JavaScript module to test connection to NAAS API.
 *
 * @copyright  2023 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('mod_naas/test_connection', ['jquery', 'core/ajax', 'core/str'], function($, ajax, Str) {

    /**
     * Handle a failed response to the test connection.
     * @param {jQuery} resultDiv - The div element to display results
     * @param {Error} error - The error object from the failed request
     * @param {Promise<string>} failedStringPromise - Promise that resolves to the failure message
     */
    function handleFailedResponse(resultDiv, error, failedStringPromise) {

        /**
         * Show an error.
         * @param {string} failedMessage
         */
        function showError(failedMessage) {
            resultDiv
                .addClass("alert alert-danger")
                .html(`<p>${failedMessage}</p><p>${error.message}</p>`)
                .show();
        }

        failedStringPromise
            .then(showError)
            .catch(() => showError('Failed!')); // Fallback message
    }

    return {
        init: function() {
            $('#testconnection').on('click', function(e) {
                e.preventDefault();
                const resultDiv = $('#connection-result');
                resultDiv.hide().removeClass();

                // Pre-fetch strings we'll need
                const successStringPromise = Str.get_string('connection_test_success', 'naas');
                const failedStringPromise = Str.get_string('connection_test_failed', 'naas');

                ajax.call([{
                    methodname: 'mod_naas_test_config',
                    args: {},
                }])[0].done(function() {
                    successStringPromise.done(function(successString) {
                        resultDiv.addClass("alert alert-success").text(successString);
                        resultDiv.show();
                    });
                }).fail(error => handleFailedResponse(resultDiv, error, failedStringPromise));
             });
        }
    };
});
